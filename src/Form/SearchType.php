<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

class SearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code_postal', TextType::class, [
                'label'    => 'Code(s) postal(aux)',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'ex: 69001 ou 69001,69002',
                    'class'       => 'form-control',
                    'id'          => 'code-postal',
                ],
                'help' => 'Filtre sur le siège social · virgule pour plusieurs codes',
            ])
            ->add('departement', TextType::class, [
                'label'    => 'Département',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'ex: 69, 01, 13',
                    'class'       => 'form-control',
                    'id'          => 'departement',
                ],
                'help' => 'Filtre sur le siège social',
            ])
            ->add('mots_cles', TextType::class, [
                'label'    => 'Recherche libre',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'Nom d\'entreprise, adresse…',
                    'class'       => 'form-control',
                    'id'          => 'mots-cles',
                ],
            ])
            ->add('code_naf', TextType::class, [
                'label'    => 'Code NAF / APE',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'ex: 6201Z, 7311Z, 7022Z',
                    'class'       => 'form-control',
                    'id'          => 'code-naf',
                ],
                'help' => '6201Z = Dév. logiciels · 7311Z = Pub · 7022Z = Conseil gestion',
            ])
            ->add('annee_creation_min', IntegerType::class, [
                'label'    => 'Créée depuis (année)',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'ex: 2015',
                    'min'         => 1900,
                    'max'         => date('Y'),
                    'class'       => 'form-control',
                    'id'          => 'annee-creation-min',
                ],
                'constraints' => [
                    new Range(min: 1900, max: (int) date('Y')),
                ],
            ])
            ->add('annee_creation_max', IntegerType::class, [
                'label'    => 'Créée jusqu\'en (année)',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'ex: ' . date('Y'),
                    'min'         => 1900,
                    'max'         => date('Y'),
                    'class'       => 'form-control',
                    'id'          => 'annee-creation-max',
                ],
                'constraints' => [
                    new Range(min: 1900, max: (int) date('Y')),
                ],
            ])
            ->add('tranche_effectif', ChoiceType::class, [
                'label'       => 'Effectif salarié',
                'required'    => false,
                'placeholder' => 'Tous',
                'choices'     => [
                    'Aucun salarié'       => '00',
                    '1 à 2 salariés'      => '01',
                    '3 à 5 salariés'      => '02',
                    '6 à 9 salariés'      => '03',
                    '10 à 19 salariés'    => '11',
                    '20 à 49 salariés'    => '12',
                    '50 à 99 salariés'    => '21',
                    '100 à 199 salariés'  => '22',
                    '200 à 249 salariés'  => '31',
                    '250 à 499 salariés'  => '32',
                    '500 à 999 salariés'  => '41',
                    '1 000 à 1 999 sal.'  => '42',
                    '2 000 sal. et plus'  => '51',
                ],
                'attr' => ['class' => 'form-control', 'id' => 'tranche-effectif'],
            ])
            ->add('categorie_juridique', ChoiceType::class, [
                'label'       => 'Forme juridique',
                'required'    => false,
                'placeholder' => 'Toutes',
                'choices'     => [
                    // 'EI' est une valeur sentinelle interne → traduite en est_entrepreneur_individuel=true
                    'Entrepreneur individuel / Auto-entrepreneur' => 'EI',
                    'SARL / EURL'    => '54',
                    'SAS / SASU'     => '57',
                    'SA'             => '55',
                    'Association'    => '65',
                    'Autre société'  => '58',
                ],
                'attr' => ['class' => 'form-control', 'id' => 'categorie-juridique'],
            ])
            ->add('sort', ChoiceType::class, [
                'label'       => 'Trier par',
                'required'    => false,
                'placeholder' => 'Pertinence (API)',
                'choices'     => [
                    'Nom A → Z'                              => 'name_asc',
                    'Nom Z → A'                              => 'name_desc',
                    'Date de création (plus récentes)'       => 'date_desc',
                    'Date de création (plus anciennes)'      => 'date_asc',
                    'Effectif salarié (croissant)'           => 'effectif_asc',
                    'Effectif salarié (décroissant)'         => 'effectif_desc',
                ],
                'attr' => ['class' => 'form-control', 'id' => 'sort-select'],
            ])
            ->add('exclure_associations', ChoiceType::class, [
                'label'    => 'Exclure les associations',
                'required' => false,
                'expanded' => true,
                'choices'  => [
                    'Oui' => '1',
                    'Non' => '0',
                ],
                'data' => '1',
                'attr' => ['class' => 'form-check-inline', 'id' => 'exclure-associations'],
            ])
            ->add('page', HiddenType::class, [
                'data' => 1,
                'attr' => ['id' => 'page-input'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'          => 'GET',
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
